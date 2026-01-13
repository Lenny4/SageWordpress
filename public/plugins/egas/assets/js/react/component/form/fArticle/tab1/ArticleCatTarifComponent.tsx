// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { ArticlePricesComponent } from "../ArticlePricesComponent";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FArticleClientInterface } from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
  toBoolean,
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
import { TOKEN } from "../../../../../token";
import { AcCoefInput } from "../inputs/AcCoef";
import { AcRemiseInput } from "../inputs/AcRemiseInput";

let translations: any = getTranslations();

type State = {
  arPrixAch: number | string;
  arCoef: number | string;
};

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const pCattarifs: any[] = Object.values(
  JSON.parse(
    $(`[data-${TOKEN}-pcattarifs]`).attr(`data-${TOKEN}-pcattarifs`) ?? "[]",
  ),
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
      for (const fArticleClient of result) {
        fArticleClient.acTypeRem = toBoolean(fArticleClient.acTypeRem);
      }
      for (const pCattarif of pCattarifs) {
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
            acTypeRem: false,
            acQteMont: 0, // remise simple
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
                  const pCattarif = pCattarifs.find(
                    (p) =>
                      p.cbIndice.toString() ===
                      fArtclient.acCategorie.toString(),
                  );
                  return {
                    item: fArtclient,
                    identifier: fArtclient.acCategorie.toString(),
                    lines: [
                      {
                        Dom: (
                          <>
                            <input
                              type="hidden"
                              name={`_${TOKEN}_${prefix}[${fArtclient.acCategorie}][acCategorie]`}
                              value={fArtclient.acCategorie}
                            />
                            {pCattarif?.ctIntitule}
                          </>
                        ),
                      },
                      {
                        Dom: (
                          <AcCoefInput
                            defaultValue={getSageMetadata(
                              `${prefix}[${fArtclient.acCategorie}][acCoef]`,
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
                          <>
                            <input
                              type="hidden"
                              name={`_${TOKEN}_${prefix}[${fArtclient.acCategorie}][acPrixTtc]`}
                              value={fArtclient.acPrixTtc}
                            />
                            <AcPrixVenInput
                              defaultValue={getSageMetadata(
                                `${prefix}[${fArtclient.acCategorie}][acPrixVen]`,
                                articleMeta,
                                fArtclient.acPrixVen,
                              )}
                              arPrixAch={arPrixAch}
                              acCoef={acCoefs[fArtclient.acCategorie]}
                              acCategorie={fArtclient.acCategorie}
                              ref={React.createRef()}
                            />
                          </>
                        ),
                      },
                      {
                        Dom: (
                          <>
                            <input
                              type="hidden"
                              name={`_${TOKEN}_${prefix}[${fArtclient.acCategorie}][acTypeRem]`}
                              value={fArtclient.acTypeRem ? 1 : 0}
                            />
                            <input
                              type="hidden"
                              name={`_${TOKEN}_${prefix}[${fArtclient.acCategorie}][acQteMont]`}
                              value={fArtclient.acQteMont}
                            />
                            <AcRemiseInput
                              defaultValue={getSageMetadata(
                                `${prefix}[${fArtclient.acCategorie}][acRemise]`,
                                articleMeta,
                                fArtclient.acRemise,
                              )}
                              acCategorie={fArtclient.acCategorie}
                              acTypeRem={fArtclient.acTypeRem}
                              acQteMont={fArtclient.acQteMont}
                              ref={React.createRef()}
                            />
                          </>
                        ),
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
    }, [arCoef, acCoefs, arPrixAch]);

    return (
      <>
        <FormContentComponent content={form.content} transPrefix="fArticles" />
        <ArticlePricesComponent />
      </>
    );
  },
);
