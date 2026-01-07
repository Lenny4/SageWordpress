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
  FormValidInterface,
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
import { AcCoefInput } from "../inputs/AcCoef";

let translations: any = getTranslations();

type State = {
  arPrixAch: number | string;
  arCoef: number | string;
};

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const pCattarifs: any[] = JSON.parse(
  $(`[data-${TOKEN}-pcattarifs]`).attr(`data-${TOKEN}-pcattarifs`) ?? "[]",
);

export const ArticleCatTarifComponent = React.forwardRef(
  ({ arPrixAch, arCoef }: State, ref) => {
    const prefix = "fArtclients";
    const [fArtclients] = React.useState<FArticleClientInterface[]>(() => {
      const result: FArticleClientInterface[] = getListObjectSageMetadata(
        prefix,
        articleMeta,
        "acCategorie",
      );
      for (const pCattarif of Object.values(pCattarifs)) {
        if (
          result.find(
            (x) => x.acCategorie.toString() === pCattarif.cbIndice.toString(),
          ) === undefined
        ) {
          result.push({
            acCategorie: pCattarif.cbIndice,
            acCoef: 1,
            acPrixVen: 0,
            acRemise: 0,
          });
        }
      }
      result.sort((a, b) => a.acCategorie - b.acCategorie);
      return result;
    });

    const getRealAcCoef = (acCoef: number | string) => {
      return acCoef.toString() === "0" ? Number(arCoef) : acCoef;
    };

    const [acCoefs, setACCoefs] = React.useState<any>(() => {
      const result: any = {};
      for (const fArtclient of fArtclients) {
        result[fArtclient.acCategorie] = getRealAcCoef(fArtclient.acCoef);
      }
      return result;
    });
    const onAcCoefChanged: TriggerFormContentChanged = (name, newValue) => {
      const acCategorie = getKeyFromName(name);
      setACCoefs((x: any) => {
        return {
          ...x,
          [acCategorie]: getRealAcCoef(Number(newValue)),
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
                    identifier: fArtclient.acCategorie.toString(),
                    lines: [
                      {
                        Dom: (
                          <>{pCattarifs[fArtclient.acCategorie]?.ctIntitule}</>
                        ),
                      },
                      {
                        Dom: (
                          <AcCoefInput
                            defaultValue={getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.acCategorie +
                                "].acCoef",
                              articleMeta,
                              getRealAcCoef(fArtclient.acCoef),
                              true,
                            )}
                            arCoef={arCoef}
                            triggerFormContentChanged={onAcCoefChanged}
                            acCategorie={fArtclient.acCategorie}
                            ref={React.createRef()}
                          />
                        ),
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
                            "][acRemise]",
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
      async isValid(): Promise<FormValidInterface> {
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
