// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FArtstockInterface,
  FDepotInterface,
} from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";
import { AsPrincipalInput } from "../inputs/AsPrincipalInput";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import { numberValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const fDepots: FDepotInterface[] = JSON.parse(
  $(`[data-${TOKEN}-fdepots]`).attr(`data-${TOKEN}-fdepots`) ?? "[]",
);

export const ArticleDepotTraitementComponent = React.forwardRef(
  (props, ref) => {
    const prefix = "fArtstocks";
    const [fArtstocks, setFArtstocks] = React.useState<FArtstockInterface[]>(
      getListObjectSageMetadata(prefix, articleMeta, "deNo"),
    );
    const [selectedDeNo, setDefaultDeNo] = React.useState<string>(() => {
      return (
        fArtstocks
          .find((x) => x.asPrincipal.toString() === "1")
          ?.deNo?.toString() ?? ""
      );
    });
    const onAsPrincipalChanged: TriggerFormContentChanged = (
      name,
      newValue,
    ) => {
      setDefaultDeNo(newValue);
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
                  "",
                  translations.words.intitule,
                  translations.words.main,
                  translations.words.asQteMini,
                  translations.words.asQteMaxi,
                ],
                removeItem: (fDepot: FDepotInterface) => {
                  setFArtstocks((v) => {
                    return v.filter(
                      (fArtstock) =>
                        fArtstock.deNo.toString() !== fDepot.deNo.toString(),
                    );
                  });
                },
                add: {
                  table: {
                    headers: [translations.words.intitule],
                    addItem: (fDepot: FDepotInterface) => {
                      setFArtstocks((v) => {
                        return [
                          ...v,
                          {
                            deNo: fDepot.deNo,
                            asPrincipal: 0,
                            asQteMaxi: 0,
                            asQteMini: 0,
                          },
                        ];
                      });
                    },
                    search: (item: FDepotInterface, search: string) => {
                      return item.deIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase());
                    },
                    items: fDepots
                      .filter(
                        (fDepot) =>
                          fArtstocks.find(
                            (fArtstock) =>
                              fArtstock.deNo.toString() ===
                              fDepot.deNo.toString(),
                          ) === undefined,
                      )
                      .map((fDepot): TableLineItemInterface => {
                        return {
                          item: fDepot,
                          lines: [
                            {
                              Dom: <span>{fDepot.deIntitule}</span>,
                            },
                          ],
                        };
                      }),
                  },
                },
                items: fArtstocks.map((fArtstock): TableLineItemInterface => {
                  const fDepot = fDepots.find(
                    (f) => f.deNo.toString() === fArtstock.deNo.toString(),
                  );
                  return {
                    item: fDepot,
                    lines: [
                      {
                        field: {
                          name: prefix + "[" + fDepot.deNo + "].deNo",
                          DomField: FormInput,
                          type: "hidden",
                          hideLabel: true,
                          initValues: {
                            value: fDepot.deNo,
                          },
                        },
                      },
                      {
                        Dom: <span>{fDepot.deIntitule}</span>,
                      },
                      {
                        // field: {
                        //   name: prefix + "[" + fDepot.deNo + "].asPrincipal",
                        //   DomField: FormCheckbox,
                        //   initValues: {
                        //     value: getSageMetadata(
                        //       prefix + "[" + fDepot.deNo + "].asPrincipal",
                        //       articleMeta,
                        //     ),
                        //   },
                        // },
                        Dom: (
                          <AsPrincipalInput
                            selectedDeNo={selectedDeNo}
                            deNo={fDepot.deNo}
                            onAsPrincipalChangedParent={onAsPrincipalChanged}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      ...["asQteMini", "asQteMaxi"].map((f) => {
                        return {
                          field: {
                            name: prefix + "[" + fDepot.deNo + "]." + f,
                            DomField: FormInput,
                            type: "number",
                            hideLabel: true,
                            initValues: {
                              value: getSageMetadata(
                                prefix + "[" + fDepot.deNo + "]." + f,
                                articleMeta,
                                // @ts-ignore
                                fArtstock[f],
                              ),
                              validator: {
                                functionName: numberValidator,
                                params: {
                                  positive: true,
                                },
                              },
                            },
                          },
                        };
                      }),
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
    }, [fArtstocks, selectedDeNo]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <FormContentComponent content={form.content} transPrefix="fArticles" />
    );
  },
);
