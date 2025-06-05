// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { ArticlePricesComponent } from "../ArticlePricesComponent";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FArticleClientInterface } from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { AcPrixVenInput } from "../inputs/AcPrixVenInput";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  getKeyFromName,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import { numberValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

type State = {
  arPrixAch: number | string;
};

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const pCattarifs: any[] = JSON.parse(
  $(`[data-${TOKEN}-pcattarifs]`).attr(`data-${TOKEN}-pcattarifs`) ?? "[]",
);

export const ArticleCatTarifComponent = React.forwardRef(
  ({ arPrixAch }: State, ref) => {
    const prefix = "fArtclients";
    const [fArtclients] = React.useState<FArticleClientInterface[]>(
      getListObjectSageMetadata(prefix, articleMeta, "acCategorie"),
    );

    const [acCoefs, setACCoefs] = React.useState<any>(() => {
      const result: any = {};
      for (const fArtclient of fArtclients) {
        result[fArtclient.acCategorie] = fArtclient.acCoef;
      }
      return result;
    });
    const onAcCoefChanged: TriggerFormContentChanged = (name, newValue) => {
      const acCategorie = getKeyFromName(name);
      setACCoefs((x: any) => {
        return {
          ...x,
          [acCategorie]: newValue,
        };
      });
    };

    const getForm = (): FormInterface => {
      return {
        content: createFormContent({
          props: {
            container: true,
            spacing: 1,
            sx: { p: 1 },
          },
          children: [
            {
              props: {
                size: { xs: 12 },
              },
              table: {
                headers: [
                  translations.words.category,
                  translations.words.coefficient,
                  translations.words.sellPrice,
                  translations.words.discount,
                ],
                items: fArtclients.map((fArtclient): TableLineItemInterface => {
                  return {
                    item: fArtclient,
                    lines: [
                      {
                        Dom: (
                          <>{pCattarifs[fArtclient.acCategorie].ctIntitule}</>
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.acCategorie + "].acCoef",
                          DomField: FormInput,
                          type: "number",
                          hideLabel: true,
                          triggerFormContentChanged: onAcCoefChanged,
                          initValues: {
                            value: getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.acCategorie +
                                "].acCoef",
                              articleMeta,
                              fArtclient.acCoef,
                            ),
                            validator: {
                              functionName: numberValidator,
                              params: {
                                canBeEmpty: true,
                              },
                            },
                          },
                        },
                      },
                      {
                        Dom: (
                          <AcPrixVenInput
                            defaultValue={getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.acCategorie +
                                "].acPrixVen",
                              articleMeta,
                              fArtclient.acPrixVen,
                            )}
                            arPrixAch={arPrixAch}
                            acCoef={acCoefs[fArtclient.acCategorie]}
                            acCategorie={fArtclient.acCategorie}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix +
                            "[" +
                            fArtclient.acCategorie +
                            "].acRemise",
                          DomField: FormInput,
                          type: "number",
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.acCategorie +
                                "].acRemise",
                              articleMeta,
                              fArtclient.acRemise,
                            ),
                            validator: {
                              functionName: numberValidator,
                              params: {
                                canBeEmpty: true,
                              },
                            },
                          },
                        },
                      },
                    ],
                  };
                }),
              },
            },
          ],
        }),
      };
    };
    const [form, setForm] = React.useState<FormInterface>(getForm());

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        return await handleFormIsValid(form.content);
      },
    }));

    React.useEffect(() => {
      setForm(getForm());
    }, [acCoefs, arPrixAch]);

    return (
      <>
        <FormContentComponent content={form.content} transPrefix="fArticles" />
        <ArticlePricesComponent />
      </>
    );
  },
);
